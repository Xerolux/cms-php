import React, { useEffect, useState } from 'react';
import {
  Table,
  Button,
  Space,
  Tag,
  Modal,
  Form,
  Input,
  Select,
  message,
  Popconfirm,
  Card,
  Row,
  Col,
} from 'antd';
import {
  PlusOutlined,
  EditOutlined,
  DeleteOutlined,
  EyeOutlined,
} from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { postService, categoryService, tagService } from '../services/api';
import type { Post, Category, Tag as TagType, PaginatedResponse } from '../types';

const { TextArea } = Input;

const PostsPage: React.FC = () => {
  const navigate = useNavigate();
  const [posts, setPosts] = useState<Post[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [tags, setTags] = useState<TagType[]>([]);
  const [loading, setLoading] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);
  const [editingPost, setEditingPost] = useState<Post | null>(null);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 10,
    total: 0,
  });
  const [form] = Form.useForm();

  useEffect(() => {
    fetchPosts();
    fetchCategories();
    fetchTags();
  }, [pagination.current, pagination.pageSize]);

  const fetchPosts = async () => {
    setLoading(true);
    try {
      const data: PaginatedResponse<Post> = await postService.getAll({
        page: pagination.current,
        per_page: pagination.pageSize,
      });
      setPosts(data.data || []);
      setPagination((prev) => ({ ...prev, total: data.total || 0 }));
    } catch (error) {
      message.error('Failed to fetch posts');
    } finally {
      setLoading(false);
    }
  };

  const fetchCategories = async () => {
    try {
      const data = await categoryService.getAll();
      setCategories(data.data || data);
    } catch (error) {
      console.error('Failed to fetch categories:', error);
    }
  };

  const fetchTags = async () => {
    try {
      const data = await tagService.getAll();
      setTags(data.data || data);
    } catch (error) {
      console.error('Failed to fetch tags:', error);
    }
  };

  const handleCreate = () => {
    setEditingPost(null);
    form.resetFields();
    setModalVisible(true);
  };

  const handleEdit = (post: Post) => {
    setEditingPost(post);
    form.setFieldsValue({
      ...post,
      category_ids: post.categories?.map((c) => c.id),
      tag_ids: post.tags?.map((t) => t.id),
    });
    setModalVisible(true);
  };

  const handleDelete = async (id: number) => {
    try {
      await postService.delete(id);
      message.success('Post deleted successfully');
      fetchPosts();
    } catch (error) {
      message.error('Failed to delete post');
    }
  };

  const handleSubmit = async () => {
    try {
      const values = await form.validateFields();

      if (editingPost) {
        await postService.update(editingPost.id, values);
        message.success('Post updated successfully');
      } else {
        await postService.create(values);
        message.success('Post created successfully');
      }

      setModalVisible(false);
      form.resetFields();
      fetchPosts();
    } catch (error) {
      message.error('Failed to save post');
    }
  };

  const columns = [
    {
      title: 'Title',
      dataIndex: 'title',
      key: 'title',
      render: (text: string, record: Post) => (
        <a onClick={() => navigate(`/posts/${record.id}/edit`)}>{text}</a>
      ),
    },
    {
      title: 'Status',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => {
        const colors: Record<string, string> = {
          published: 'green',
          draft: 'default',
          scheduled: 'blue',
          archived: 'red',
        };
        return <Tag color={colors[status]}>{status.toUpperCase()}</Tag>;
      },
    },
    {
      title: 'Categories',
      dataIndex: 'categories',
      key: 'categories',
      render: (categories: Category[]) => (
        <div>
          {categories?.map((cat) => (
            <Tag key={cat.id}>{cat.name}</Tag>
          ))}
        </div>
      ),
    },
    {
      title: 'Views',
      dataIndex: 'view_count',
      key: 'view_count',
    },
    {
      title: 'Created',
      dataIndex: 'created_at',
      key: 'created_at',
      render: (date: string) => new Date(date).toLocaleDateString(),
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_: any, record: Post) => (
        <Space>
          <Button
            type="link"
            icon={<EyeOutlined />}
            onClick={() => navigate(`/posts/${record.id}`)}
          />
          <Button
            type="link"
            icon={<EditOutlined />}
            onClick={() => handleEdit(record)}
          />
          <Popconfirm
            title="Are you sure you want to delete this post?"
            onConfirm={() => handleDelete(record.id)}
            okText="Yes"
            cancelText="No"
          >
            <Button type="link" danger icon={<DeleteOutlined />} />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div>
      <Card
        title="Posts Management"
        extra={
          <Button type="primary" icon={<PlusOutlined />} onClick={handleCreate}>
            New Post
          </Button>
        }
      >
        <Table
          columns={columns}
          dataSource={posts}
          rowKey="id"
          loading={loading}
          pagination={{
            current: pagination.current,
            pageSize: pagination.pageSize,
            total: pagination.total,
            onChange: (page, pageSize) =>
              setPagination((prev) => ({ ...prev, current: page, pageSize: pageSize || 10 }))
            ,
          }}
        />
      </Card>

      <Modal
        title={editingPost ? 'Edit Post' : 'Create Post'}
        open={modalVisible}
        onOk={handleSubmit}
        onCancel={() => {
          setModalVisible(false);
          form.resetFields();
        }}
        width={800}
        okText="Save"
      >
        <Form form={form} layout="vertical">
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item
                name="title"
                label="Title"
                rules={[{ required: true, message: 'Please enter title' }]}
              >
                <Input placeholder="Post title" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item
                name="status"
                label="Status"
                rules={[{ required: true }]}
              >
                <Select>
                  <Select.Option value="draft">Draft</Select.Option>
                  <Select.Option value="published">Published</Select.Option>
                  <Select.Option value="scheduled">Scheduled</Select.Option>
                  <Select.Option value="archived">Archived</Select.Option>
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <Form.Item
            name="content"
            label="Content"
            rules={[{ required: true, message: 'Please enter content' }]}
          >
            <TextArea rows={10} placeholder="Post content (Markdown or HTML)" />
          </Form.Item>

          <Form.Item name="excerpt" label="Excerpt">
            <TextArea rows={3} placeholder="Short excerpt" />
          </Form.Item>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="category_ids" label="Categories">
                <Select mode="multiple" options={categories.map(c => ({ label: c.name, value: c.id }))} />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="tag_ids" label="Tags">
                <Select mode="multiple" options={tags.map(t => ({ label: t.name, value: t.id }))} />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="meta_title" label="Meta Title">
                <Input placeholder="SEO title" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="meta_description" label="Meta Description">
                <Input placeholder="SEO description" />
              </Form.Item>
            </Col>
          </Row>
        </Form>
      </Modal>
    </div>
  );
};

export default PostsPage;
